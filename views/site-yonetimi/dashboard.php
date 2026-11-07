<?php
require_once dirname(__DIR__, 1) . '/../Autoloader.php';

$maintitle = 'Site Yönetimi';
$title = 'Dashboard';
?>
<script>try { document.documentElement.classList.toggle('sitedash-summary-hidden', localStorage.getItem('sitedash_summary_cards_state') === 'hidden'); } catch (e) {}</script>
<style>
#summaryCardsContainer { overflow: hidden; max-height: 1100px; opacity: 1; transition: max-height .3s ease, opacity .3s ease, margin .3s ease; }
.sitedash-summary-hidden #summaryCardsContainer { max-height: 0 !important; opacity: 0; margin-top: 0 !important; margin-bottom: 0 !important; pointer-events: none; }
@media (prefers-reduced-motion: reduce) { #summaryCardsContainer { transition: none; } }
.sd-thumb { width: 54px; height: 38px; object-fit: cover; border-radius: 8px; border: 1px solid var(--bs-border-color, #e3e8ef); background: var(--bs-secondary-bg, #eef1f5); flex: none; }
.sd-satir { display: flex; align-items: center; gap: 12px; padding: 10px 0; border-bottom: 1px dashed var(--bs-border-color, #e2e8f0); }
.sd-satir:last-child { border-bottom: 0; }
.sd-satir a { text-decoration: none; color: inherit; }
.sd-hizli { display: flex; align-items: center; gap: 12px; padding: 12px 14px; border: 1px solid var(--bs-border-color, #e3e8ef); border-radius: 12px; text-decoration: none; color: inherit; transition: border-color .15s ease, background .15s ease; }
.sd-hizli:hover { border-color: var(--bs-primary, #556ee6); background: var(--bs-primary-bg-subtle, #e8ecfd); color: inherit; }
.sd-hizli .ico { width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex: none; }
.sd-bos { padding: 28px 0; text-align: center; color: #74788d; font-size: 13px; }
</style>

<script>window.siteDashIlk = fetch('views/site-yonetimi/api.php', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: 'action=dashboard-data' }).then(function (r) { return r.json(); }).catch(function () { return null; });</script>

<?php include 'layouts/breadcrumb.php'; ?>

<div class="container-fluid">
    <div class="row align-items-center mb-3">
        <div class="col-md-6 col-12 d-flex align-items-center gap-3">
            <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 44px; height: 44px;">
                <i class="bx bx-globe fs-4 text-primary"></i>
            </div>
            <div>
                <h4 class="mb-0 fw-bold text-dark font-size-16">Site Yönetimi</h4>
                <p class="text-muted mb-0 font-size-12">Kurumsal web sitesi içeriğine genel bakış ve hızlı işlemler</p>
            </div>
        </div>
        <div class="personel-action-toolbar col-md-6 col-12 d-flex align-items-center justify-content-md-end gap-2 mt-2 mt-md-0">
            <a href="index.php?p=site-yonetimi/hizmetler&yeni=1" class="btn btn-primary top-action-btn shadow-sm text-white d-none" id="btnYeniHizmetLink">
                <i class="bx bx-plus font-size-16"></i> Yeni Hizmet
            </a>
            <button type="button" class="btn btn-outline-secondary bg-white top-icon-btn shadow-sm" id="btnToggleSummaryCards" title="Özet Kartları Göster/Gizle" aria-label="Özet kartlarını göster veya gizle" aria-expanded="true">
                <i class="bx bx-chevron-up"></i>
            </button>
        </div>
    </div>

    <div class="row g-3 mb-3 summary-cards-group" id="summaryCardsContainer">
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0"><div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="summary-kpi-label">TOPLAM HİZMET</span>
                    <div class="summary-kpi-icon bg-primary-subtle text-primary border border-primary-subtle"><i class="bx bx-wrench"></i></div>
                </div>
                <h3 class="summary-kpi-value my-1" id="stat_toplam">-</h3>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="summary-kpi-subtext">Silinenler hariç</span>
                    <a href="index.php?p=site-yonetimi/hizmetler" class="btn btn-sm btn-subtle-primary rounded-pill px-2 py-0 d-flex align-items-center gap-1 summary-pill-btn d-none" data-izin="hizmet"><i class="bx bx-layer"></i> Tümü</a>
                </div>
            </div></div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0"><div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="summary-kpi-label">YAYINDAKİ</span>
                    <div class="summary-kpi-icon bg-success-subtle text-success border border-success-subtle"><i class="bx bx-broadcast"></i></div>
                </div>
                <h3 class="summary-kpi-value my-1 text-success" id="stat_yayinda">-</h3>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="summary-kpi-subtext text-success fw-semibold">Sitede görünüyor</span>
                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">Canlı</span>
                </div>
            </div></div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0"><div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="summary-kpi-label">TASLAK</span>
                    <div class="summary-kpi-icon bg-info-subtle text-info border border-info-subtle"><i class="bx bx-edit"></i></div>
                </div>
                <h3 class="summary-kpi-value my-1" id="stat_taslak">-</h3>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="summary-kpi-subtext">Henüz yayınlanmadı</span>
                    <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">Bekliyor</span>
                </div>
            </div></div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0"><div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="summary-kpi-label">GÖRSELİ OLMAYAN</span>
                    <div class="summary-kpi-icon bg-warning-subtle text-warning border border-warning-subtle"><i class="bx bx-image"></i></div>
                </div>
                <h3 class="summary-kpi-value my-1" id="stat_gorselsiz">-</h3>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="summary-kpi-subtext text-muted">Görsel eklenmeli</span>
                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">Eksik</span>
                </div>
            </div></div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-xl-5">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-header bg-transparent border-0 px-3 py-3">
                    <h5 class="card-title mb-0 font-size-14 fw-bold text-dark">Hızlı İşlemler</h5>
                    <p class="text-muted mb-0 font-size-12">Sık kullanılan site yönetimi işlemleri</p>
                </div>
                <div class="card-body pt-0 d-flex flex-column gap-2">
                    <a href="index.php?p=site-yonetimi/hizmetler&yeni=1" class="sd-hizli d-none" data-izin="hizmet">
                        <span class="ico bg-primary-subtle text-primary"><i class="bx bx-plus fs-5"></i></span>
                        <span><span class="fw-semibold d-block">Yeni Hizmet Ekle</span><span class="text-muted font-size-12">Sitede yayınlanacak yeni bir hizmet oluşturun</span></span>
                    </a>
                    <a href="index.php?p=site-yonetimi/hizmetler" class="sd-hizli d-none" data-izin="hizmet">
                        <span class="ico bg-success-subtle text-success"><i class="bx bx-wrench fs-5"></i></span>
                        <span><span class="fw-semibold d-block">Hizmetleri Yönet</span><span class="text-muted font-size-12">Düzenle, yayınla veya yayından kaldır</span></span>
                    </a>
                    <a href="index.php?p=site-yonetimi/projeler" class="sd-hizli d-none" data-izin="proje">
                        <span class="ico bg-info-subtle text-info"><i class="bx bx-briefcase-alt-2 fs-5"></i></span>
                        <span><span class="fw-semibold d-block">Projeleri Yönet</span><span class="text-muted font-size-12" id="projeOzet">Sitede gösterilen projeler</span></span>
                    </a>
                    <a href="index.php?p=site-yonetimi/referanslar" class="sd-hizli d-none" data-izin="referans">
                        <span class="ico bg-success-subtle text-success"><i class="bx bx-award fs-5"></i></span>
                        <span><span class="fw-semibold d-block">Referansları Yönet</span><span class="text-muted font-size-12" id="referansOzet">Müşteri yorumları ve logoları</span></span>
                    </a>
                    <a href="index.php?p=site-yonetimi/sss" class="sd-hizli d-none" data-izin="sss">
                        <span class="ico bg-warning-subtle text-warning"><i class="bx bx-help-circle fs-5"></i></span>
                        <span><span class="fw-semibold d-block">Sık Sorulanları Yönet</span><span class="text-muted font-size-12" id="sssOzet">Sitedeki SSS bölümü</span></span>
                    </a>
                    <a href="index.php?p=site-yonetimi/istatistikler" class="sd-hizli d-none" data-izin="istatistik">
                        <span class="ico bg-primary-subtle text-primary"><i class="bx bx-bar-chart-alt-2 fs-5"></i></span>
                        <span><span class="fw-semibold d-block">İstatistikleri Yönet</span><span class="text-muted font-size-12" id="istatistikOzet">Sitedeki sayaçlı rakamlar</span></span>
                    </a>
                    <a href="index.php?p=site-yonetimi/ayarlar" class="sd-hizli d-none" data-izin="ayar">
                        <span class="ico bg-secondary-subtle text-secondary"><i class="bx bx-cog fs-5"></i></span>
                        <span><span class="fw-semibold d-block">Site Ayarları</span><span class="text-muted font-size-12">İletişim bilgileri, hakkımızda, slayt metinleri</span></span>
                    </a>
                    <a href="index.php?p=site-yonetimi/mesajlar" class="sd-hizli d-none" data-izin="mesaj">
                        <span class="ico bg-danger-subtle text-danger"><i class="bx bx-envelope fs-5"></i></span>
                        <span><span class="fw-semibold d-block">Gelen Mesajlar <span class="badge bg-danger rounded-pill ms-1 d-none" id="mesajYeniRozet">0</span></span><span class="text-muted font-size-12" id="mesajOzet">Sitedeki iletişim formundan gelen mesajlar</span></span>
                    </a>
                    <a href="index.php?p=ik-ise-alim/ilanlar" class="sd-hizli d-none" data-izin="ilan">
                        <span class="ico bg-warning-subtle text-warning"><i class="bx bx-briefcase-alt-2 fs-5"></i></span>
                        <span><span class="fw-semibold d-block">İş İlanları (Kariyer Sayfası)</span><span class="text-muted font-size-12" id="kariyerOzet">Sitedeki kariyer sayfasını besleyen ilanlar</span></span>
                    </a>
                    <div class="sd-bos d-none" id="hizliBos">Bu bölümde işlem yapma yetkiniz bulunmuyor.</div>
                </div>
            </div>
        </div>
        <div class="col-xl-7">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-header bg-transparent border-0 px-3 py-3">
                    <h5 class="card-title mb-0 font-size-14 fw-bold text-dark">Son Güncellenen Hizmetler</h5>
                    <p class="text-muted mb-0 font-size-12">En son değişiklik yapılan 5 hizmet</p>
                </div>
                <div class="card-body pt-0" id="sonHizmetler"><div class="sd-bos">Yükleniyor...</div></div>
            </div>
        </div>
    </div>

    <div class="card summary-kpi-card mb-3 d-none" id="sonIslemlerKart">
        <div class="card-header bg-transparent border-0 px-3 py-3">
            <h5 class="card-title mb-0 font-size-14 fw-bold text-dark">Son İşlemler</h5>
            <p class="text-muted mb-0 font-size-12">Site yönetiminde yapılan son değişiklikler</p>
        </div>
        <div class="card-body pt-0" id="sonIslemler"></div>
    </div>
</div>

<script src="views/site-yonetimi/js/dashboard.js?v=<?php echo time(); ?>"></script>
