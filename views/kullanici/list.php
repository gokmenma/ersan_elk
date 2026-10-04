<?php

require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Helper\Helper;
use App\Helper\Security;
use App\Model\UserModel;

$User = new UserModel();
$users = $User->getUsers();

// İstatistikler
$toplamKullanici = count($users);
$aktifSayisi = 0;
$pasifSayisi = 0;
$izinOnayciSayisi = 0;

foreach ($users as $u) {
    if (($u->durum ?? '') === 'Aktif') {
        $aktifSayisi++;
    } else {
        $pasifSayisi++;
    }
    if (($u->izin_onayi_yapacakmi ?? '') === 'Evet') {
        $izinOnayciSayisi++;
    }
}

$maintitle = "Kullanıcı Yönetimi";
$title = "Kullanıcı Listesi";
?>
<script>try { document.documentElement.classList.toggle('kullanici-summary-hidden', localStorage.getItem('kullanici_summary_cards_state') === 'hidden'); } catch (e) {}</script>
<style>
#summaryCardsContainer { overflow: hidden; max-height: 1100px; opacity: 1; transition: max-height .3s ease, opacity .3s ease, margin .3s ease; }
.kullanici-summary-hidden #summaryCardsContainer { max-height: 0 !important; opacity: 0; margin-top: 0 !important; margin-bottom: 0 !important; pointer-events: none; }
@media (prefers-reduced-motion: reduce) { #summaryCardsContainer { transition: none; } }
</style>

<?php include 'layouts/breadcrumb.php'; ?>

<div class="container-fluid">
    <!-- 1. Üst Başlık ve Aksiyon Araç Çubuğu (Yeni Standart) -->
    <div class="row align-items-center mb-3">
        <div class="col-md-6 col-12 d-flex align-items-center gap-3">
            <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 44px; height: 44px;">
                <i class="bx bx-user-pin fs-4 text-primary"></i>
            </div>
            <div>
                <h4 class="mb-0 fw-bold text-dark font-size-16">Kullanıcı Yönetimi</h4>
                <p class="text-muted mb-0 font-size-12">Sistem kullanıcıları, yetki rolleri ve hesap erişim kontrolleri</p>
            </div>
        </div>
        
        <div class="personel-action-toolbar col-md-6 col-12 d-flex align-items-center justify-content-md-end gap-2 mt-2 mt-md-0">
            <!-- 1. Yeni Kullanıcı Ekle Butonu -->
            <button type="button" class="btn btn-primary top-action-btn shadow-sm text-white" id="userAddBtn">
                <i class="bx bx-plus font-size-16"></i> Yeni Kullanıcı Ekle
            </button>

            <!-- 2. Yetki Grupları Linki -->
            <a href="index?p=kullanici-gruplari/list" class="btn btn-outline-secondary bg-white top-action-btn shadow-sm">
                <i class="bx bx-shield-quarter font-size-16 text-primary"></i> Yetki Grupları
            </a>

            <!-- 3. Özet Kartları Açma/Kapama Butonu -->
            <button type="button" class="btn btn-outline-secondary bg-white top-icon-btn shadow-sm" id="btnToggleSummaryCards" title="Özet Kartları Göster/Gizle" aria-expanded="true">
                <i class="bx bx-chevron-up"></i>
            </button>
        </div>
    </div>

    <!-- 2. 4 Adet Minimal Özet KPI Kartı -->
    <div class="row g-3 mb-3 summary-cards-group" id="summaryCardsContainer">
        <!-- Kart 1: TOPLAM KULLANICI -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">TOPLAM KULLANICI</span>
                        <div class="summary-kpi-icon bg-primary-subtle text-primary border border-primary-subtle">
                            <i class="bx bx-user"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1" id="stat_toplam"><?= $toplamKullanici; ?> Kişi</h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext" id="stat_sub_toplam">Aktif: <?= $aktifSayisi; ?> | Pasif: <?= $pasifSayisi; ?></span>
                        <button type="button" class="btn btn-sm btn-subtle-primary rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn active" data-filter-durum="all">
                            <i class="bx bx-layer"></i> Tümü
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kart 2: AKTİF HESAPLAR -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">AKTİF HESAPLAR</span>
                        <div class="summary-kpi-icon bg-success-subtle text-success border border-success-subtle">
                            <i class="bx bx-check-circle"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1 text-success" id="stat_aktif"><?= $aktifSayisi; ?> Kişi</h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext text-success fw-semibold">Erişime Açık</span>
                        <button type="button" class="btn btn-sm btn-subtle-success rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn" data-filter-durum="Aktif">
                            <i class="bx bx-check"></i> Aktif
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kart 3: PASİF HESAPLAR -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">PASİF HESAPLAR</span>
                        <div class="summary-kpi-icon bg-danger-subtle text-danger border border-danger-subtle">
                            <i class="bx bx-x-circle"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1 text-danger" id="stat_pasif"><?= $pasifSayisi; ?> Kişi</h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext text-danger fw-semibold">Erişime Kapalı</span>
                        <button type="button" class="btn btn-sm btn-subtle-danger rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn" data-filter-durum="Pasif">
                            <i class="bx bx-x"></i> Pasif
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kart 4: İZİN ONAY YETKİLİLERİ -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">İZİN ONAY YETKİSİ</span>
                        <div class="summary-kpi-icon bg-warning-subtle text-warning border border-warning-subtle">
                            <i class="bx bx-check-shield"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1 text-warning" id="stat_izin"><?= $izinOnayciSayisi; ?> Kişi</h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext text-warning fw-semibold">Onay Sırası Tanımlı</span>
                        <button type="button" class="btn btn-sm btn-subtle-warning rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn" data-filter-durum="izin_onay">
                            <i class="bx bx-shield"></i> Onaycılar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. Standart DataTables Kullanıcı Listesi Kartı -->
    <div class="card summary-kpi-card mb-3" id="kullaniciListCard">
        <div class="card-header bg-transparent border-0 px-3 py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 38px; height: 38px;">
                    <i class="bx bx-list-ul font-size-20"></i>
                </div>
                <div>
                    <h5 class="card-title mb-0 font-size-14 fw-bold text-dark">Kullanıcı Listesi</h5>
                    <p class="text-muted mb-0 font-size-12" style="margin-top: 2px;">Anlık arama, canlı sütun filtreleme ve yetki yönetimi</p>
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
                <table id="usersTable" class="table table-bordered table-hover nowrap align-middle w-100 mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="text-center" style="width: 45px;" data-filter="none">#</th>
                            <th data-filter="string">KULLANICI</th>
                            <th style="width: 140px;" data-filter="select">ROLLER</th>
                            <th data-filter="string">GÖREVİ</th>
                            <th data-filter="string">İLETİŞİM</th>
                            <th class="text-center" style="width: 120px;" data-filter="select">İZİN ONAYI</th>
                            <th class="text-center" style="width: 85px;" data-filter="select">DURUM</th>
                            <th style="width: 100px;" data-filter="date">KAYIT TARİHİ</th>
                            <th class="text-center" style="min-width: 80px; width: 90px;" data-filter="none">İŞLEMLER</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $i = 0;
                        foreach ($users as $user):
                            $i++;
                            $enc_id = Security::encrypt($user->id);
                            $durum = (string) ($user->durum ?? 'Aktif');
                            $isAktif = ($durum === 'Aktif');
                            $izinOnayi = (string) ($user->izin_onayi_yapacakmi ?? 'Hayır');
                            $izinOnaySirasi = (int) ($user->izin_onay_sirasi ?? 0);
                        ?>
                            <tr data-id="<?= htmlspecialchars($enc_id, ENT_QUOTES, 'UTF-8'); ?>"
                                data-durum="<?= htmlspecialchars($durum, ENT_QUOTES, 'UTF-8'); ?>"
                                data-izin-onayi="<?= htmlspecialchars($izinOnayi, ENT_QUOTES, 'UTF-8'); ?>">
                                <td class="text-center">
                                    <span class="fw-bold text-muted"><?= $i; ?></span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="avatar-xs bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 32px; height: 32px; font-size: 13px;">
                                            <i class="bx bx-user"></i>
                                        </div>
                                        <div class="min-w-0">
                                            <a href="javascript:void(0);" class="fw-bold text-dark font-size-13 text-decoration-none d-block text-truncate kullanici-duzenle" data-id="<?= htmlspecialchars($enc_id, ENT_QUOTES, 'UTF-8'); ?>" style="max-width: 200px;">
                                                <?= htmlspecialchars((string) ($user->adi_soyadi ?? '-'), ENT_QUOTES, 'UTF-8'); ?>
                                            </a>
                                            <span class="text-muted font-size-11 d-flex align-items-center gap-1">
                                                <i class="bx bx-at"></i> <?= htmlspecialchars((string) ($user->user_name ?? '-'), ENT_QUOTES, 'UTF-8'); ?>
                                            </span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php
                                    $names = !empty($user->role_names) ? explode(',', $user->role_names) : [];
                                    $colors = !empty($user->role_colors) ? explode(',', $user->role_colors) : [];
                                    if ($names === []):
                                    ?>
                                        <span class="text-muted small">-</span>
                                    <?php else: ?>
                                        <div class="d-flex flex-wrap gap-1">
                                            <?php foreach ($names as $key => $name):
                                                $color = $colors[$key] ?? 'secondary';
                                            ?>
                                                <span class="badge bg-<?= htmlspecialchars($color, ENT_QUOTES, 'UTF-8'); ?>-subtle text-<?= htmlspecialchars($color, ENT_QUOTES, 'UTF-8'); ?> border border-<?= htmlspecialchars($color, ENT_QUOTES, 'UTF-8'); ?>-subtle rounded-pill px-2 py-0.5 font-size-11 fw-semibold">
                                                    <?= htmlspecialchars(trim($name), ENT_QUOTES, 'UTF-8'); ?>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="font-size-12 fw-semibold text-dark"><?= htmlspecialchars((string) ($user->gorevi ?? '-'), ENT_QUOTES, 'UTF-8'); ?></span>
                                </td>
                                <td>
                                    <div class="d-flex flex-column">
                                        <?php if (!empty($user->email_adresi)): ?>
                                            <span class="font-size-12 text-dark d-flex align-items-center gap-1">
                                                <i class="bx bx-envelope text-muted"></i> <?= htmlspecialchars((string) $user->email_adresi, ENT_QUOTES, 'UTF-8'); ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if (!empty($user->telefon)): ?>
                                            <span class="text-muted font-size-11 d-flex align-items-center gap-1 mt-0.5">
                                                <i class="bx bx-phone"></i> <?= htmlspecialchars((string) $user->telefon, ENT_QUOTES, 'UTF-8'); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <?php if ($izinOnayi === 'Evet'): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold" title="İzin Onay Sırası: <?= $izinOnaySirasi; ?>">
                                            <i class="bx bx-check-double me-0.5"></i> Evet (Sıra: <?= $izinOnaySirasi; ?>)
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-muted border rounded-pill px-2 py-1 font-size-11 fw-medium">
                                            Hayır
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($isAktif): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold durum-degistir"
                                              data-id="<?= htmlspecialchars($enc_id, ENT_QUOTES, 'UTF-8'); ?>"
                                              data-status="Pasif"
                                              style="cursor: pointer;"
                                              title="Durumu değiştirmek için tıklayın">
                                            <i class="bx bx-check me-0.5"></i> Aktif
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold durum-degistir"
                                              data-id="<?= htmlspecialchars($enc_id, ENT_QUOTES, 'UTF-8'); ?>"
                                              data-status="Aktif"
                                              style="cursor: pointer;"
                                              title="Durumu değiştirmek için tıklayın">
                                            <i class="bx bx-x me-0.5"></i> Pasif
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="font-size-12 text-dark fw-medium">
                                        <?= !empty($user->created_at) ? date('d.m.Y', strtotime($user->created_at)) : '-'; ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <div class="action-btn-group d-flex align-items-center justify-content-center gap-1">
                                        <button type="button" class="btn btn-subtle-warning table-action-btn kullanici-duzenle" data-id="<?= htmlspecialchars($enc_id, ENT_QUOTES, 'UTF-8'); ?>" title="Kullanıcıyı Düzenle">
                                            <i class="bx bx-edit-alt"></i>
                                        </button>
                                        <button type="button" class="btn btn-subtle-danger table-action-btn kullanici-sil" data-id="<?= htmlspecialchars($enc_id, ENT_QUOTES, 'UTF-8'); ?>" data-name="<?= htmlspecialchars((string) ($user->adi_soyadi ?? ''), ENT_QUOTES, 'UTF-8'); ?>" title="Kullanıcıyı Sil">
                                            <i class="bx bx-trash"></i>
                                        </button>
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

<!-- Kullanıcı İşlem Modalı -->
<div class="modal fade" id="userModal" tabindex="-1" aria-labelledby="userModalLabel" aria-hidden="true" style="z-index: 1060;">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable user-modal-dialog-custom">
        <div class="modal-content user-modal-content border-0 shadow">
            <!-- Spinner -->
            <div class="d-flex justify-content-center align-items-center" style="height: 400px;">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Yükleniyor...</span>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Tablo Tipografi ve Okunabilirlik İyileştirmeleri */
#usersTable {
    font-size: 13px !important;
}
#usersTable thead th {
    font-size: 11.5px !important;
    font-weight: 700 !important;
    color: #334155 !important;
    letter-spacing: 0.3px;
    background-color: #f8fafc !important;
    vertical-align: middle !important;
}
#usersTable tbody td {
    padding: 8px 12px !important;
    vertical-align: middle !important;
    color: #0f172a !important;
}

#usersTable tbody tr {
    cursor: pointer;
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
</style>

<script src="<?= Helper::assetVersion('views/kullanici/js/user.js'); ?>"></script>