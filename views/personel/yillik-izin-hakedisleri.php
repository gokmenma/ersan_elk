<?php

require_once dirname(__DIR__, 2) . '/Autoloader.php';
use App\Helper\Form;
use App\Model\YillikIzinHakedisModel;
use App\Service\Gate;

$maintitle = "Personel";
$title = "Yıllık İzin Hakedişleri";

$HakedisModel = new YillikIzinHakedisModel();
$summary = $HakedisModel->summary((int) ($_SESSION['firma_id'] ?? 0));
$duzenleyebilir = Gate::allows('personel/yillik-izin-hakedisleri-duzenle');
$personelSecenekleri = $duzenleyebilir ? $HakedisModel->aktifPersoneller((int) ($_SESSION['firma_id'] ?? 0)) : [];
$gunFmt = fn($v) => rtrim(rtrim(number_format((float) $v, 1, ',', '.'), '0'), ',');
?>
<script>try { document.documentElement.classList.toggle('yillikizin-summary-hidden', localStorage.getItem('yillikizin_summary_cards_state') === 'hidden'); } catch (e) {}</script>
<style>
#summaryCardsContainer { overflow: hidden; max-height: 1100px; opacity: 1; transition: max-height .3s ease, opacity .3s ease, margin .3s ease; }
.yillikizin-summary-hidden #summaryCardsContainer { max-height: 0 !important; opacity: 0; margin-top: 0 !important; margin-bottom: 0 !important; pointer-events: none; }
@media (prefers-reduced-motion: reduce) { #summaryCardsContainer { transition: none; } }
#yillikIzinTable tbody tr { cursor: pointer; }
</style>

<?php include 'layouts/breadcrumb.php'; ?>

<div class="container-fluid">
    <div class="row align-items-center mb-3">
        <div class="col-md-6 col-12 d-flex align-items-center gap-3">
            <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 44px; height: 44px;">
                <i class="bx bx-calendar-star fs-4 text-primary"></i>
            </div>
            <div>
                <h4 class="mb-0 fw-bold text-dark font-size-16">Yıllık İzin Hakedişleri</h4>
                <p class="text-muted mb-0 font-size-12">İş Kanunu 53. madde kıdem esaslı hakediş, kullanılan ve kalan yıllık izin günleri</p>
            </div>
        </div>

        <div class="personel-action-toolbar col-md-6 col-12 d-flex align-items-center justify-content-md-end gap-2 mt-2 mt-md-0">
            <?php if ($duzenleyebilir): ?>
            <button type="button" class="btn btn-primary top-action-btn shadow-sm text-white" id="btnManuelEkle" data-bs-toggle="modal" data-bs-target="#manuelModal">
                <i class="bx bx-plus font-size-16"></i> Manuel Hakediş Ekle
            </button>
            <button type="button" class="btn btn-outline-secondary bg-white top-action-btn shadow-sm" data-bs-toggle="modal" data-bs-target="#excelModal">
                <i class="bx bx-upload font-size-16"></i> Excel ile Yükle
            </button>
            <?php endif; ?>
            <div class="form-check form-switch mb-0 me-2">
                <input class="form-check-input" type="checkbox" role="switch" id="pasifDahil">
                <label class="form-check-label font-size-12" for="pasifDahil">Pasif personeli de göster</label>
            </div>
            <button type="button" class="btn btn-outline-secondary bg-white top-icon-btn shadow-sm" id="btnToggleSummaryCards" title="Özet Kartları Göster/Gizle" aria-label="Özet kartlarını gizle" aria-expanded="true">
                <i class="bx bx-chevron-up"></i>
            </button>
        </div>
    </div>

    <div class="row g-3 mb-3 summary-cards-group" id="summaryCardsContainer">
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">AKTİF PERSONEL</span>
                        <div class="summary-kpi-icon bg-primary-subtle text-primary border border-primary-subtle"><i class="bx bx-group"></i></div>
                    </div>
                    <h3 class="summary-kpi-value my-1" id="kpi_toplam"><?php echo (int) $summary->toplam_personel; ?></h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext" id="kpi_sub_toplam">Hakedişi olan: <?php echo (int) $summary->hakedisi_olan; ?> | Yok: <?php echo (int) $summary->hakedis_yok; ?></span>
                        <button type="button" class="btn btn-sm btn-subtle-primary rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn active" data-status="all">
                            <i class="bx bx-list-ul"></i> Tümü
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">KULLANILABİLİR İZNİ OLAN</span>
                        <div class="summary-kpi-icon bg-success-subtle text-success border border-success-subtle"><i class="bx bx-check-circle"></i></div>
                    </div>
                    <h3 class="summary-kpi-value my-1 text-success" id="kpi_kullanilabilir"><?php echo (int) $summary->kullanilabilir; ?></h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext text-success fw-semibold">Kalan bakiyesi bulunan</span>
                        <button type="button" class="btn btn-sm btn-subtle-success rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn" data-status="kullanilabilir">
                            <i class="bx bx-check-circle"></i> Kullanılabilir
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">İZNİ TÜKENEN</span>
                        <div class="summary-kpi-icon bg-danger-subtle text-danger border border-danger-subtle"><i class="bx bx-block"></i></div>
                    </div>
                    <h3 class="summary-kpi-value my-1 text-danger" id="kpi_tukendi"><?php echo (int) $summary->tukendi; ?></h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext" id="kpi_sub_yok">Hakedişi olmayan: <?php echo (int) $summary->hakedis_yok; ?></span>
                        <button type="button" class="btn btn-sm btn-subtle-danger rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn" data-status="tukendi">
                            <i class="bx bx-minus-circle"></i> Tükendi
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">TOPLAM KALAN İZİN (GÜN)</span>
                        <div class="summary-kpi-icon bg-warning-subtle text-warning border border-warning-subtle"><i class="bx bx-calendar"></i></div>
                    </div>
                    <h3 class="summary-kpi-value my-1" id="kpi_kalan"><?php echo htmlspecialchars($gunFmt($summary->toplam_kalan), ENT_QUOTES, 'UTF-8'); ?></h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext" id="kpi_sub_kalan">Hakediş: <?php echo htmlspecialchars($gunFmt($summary->toplam_hakedis), ENT_QUOTES, 'UTF-8'); ?> | Kullanılan: <?php echo htmlspecialchars($gunFmt($summary->toplam_kullanilan), ENT_QUOTES, 'UTF-8'); ?></span>
                        <button type="button" class="btn btn-sm btn-outline-warning rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn" data-status="hakedis_yok">
                            <i class="bx bx-error-circle"></i> Hakedişi Yok
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0" id="yillikIzinListCard">
        <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
                <div class="bg-primary-subtle text-primary rounded-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                    <i class="bx bx-list-ul font-size-18"></i>
                </div>
                <div>
                    <h5 class="card-title font-size-14 fw-bold mb-0 text-dark">Hakediş Listesi</h5>
                    <p class="text-muted font-size-11 mb-0">Anlık filtreleme ve sıralama; satıra tıklayarak yıllara göre hakediş detayını görebilirsiniz</p>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn btn-sm btn-outline-secondary bg-white shadow-sm" id="btnHeaderPrint" title="Yazdır">
                    <i class="bx bx-printer font-size-14 me-1"></i> Yazdır
                </button>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table id="yillikIzinTable" class="table table-bordered table-hover nowrap align-middle w-100 mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="text-center" style="width: 50px;" data-filter="none">Sıra</th>
                            <th data-filter="string">Personel</th>
                            <th data-filter="string">Departman</th>
                            <th data-filter="string">Görev</th>
                            <th class="text-center" style="width: 110px;" data-filter="none">İşe Giriş</th>
                            <th class="text-center" style="width: 90px;" data-filter="none">Kıdem</th>
                            <th class="text-center" style="width: 100px;" data-filter="none">Toplam Hakediş</th>
                            <th class="text-center" style="width: 90px;" data-filter="none">Kullanılan</th>
                            <th class="text-center" style="width: 80px;" data-filter="none">Kalan</th>
                            <th class="text-center" style="width: 120px;" data-filter="select" data-filter-options="Kullanılabilir|Tükendi|Hakediş Yok">Durum</th>
                            <th data-filter="none">Sonraki Hakediş</th>
                            <th class="text-center" style="width: 80px;" data-filter="none">İşlem</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="detayModal" tabindex="-1" aria-labelledby="detayModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="detayModalLabel"><i class="bx bx-calendar-star me-2"></i>Yıllık İzin Hakediş Detayı</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body" id="detayModalBody"></div>
        </div>
    </div>
</div>

<?php if ($duzenleyebilir): ?>
<div class="modal fade" id="manuelModal" tabindex="-1" aria-labelledby="manuelModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="manuelModalLabel"><i class="bx bx-calendar-plus me-2"></i>Manuel Yıllık İzin Hakedişi Ekle</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body">
                <form id="manuelForm" autocomplete="off">
                    <div class="mb-3">
                        <?php echo Form::FormSelect2('personel_id', ['' => 'Personel seçiniz'] + $personelSecenekleri, '', 'Personel *', 'bx bx-user', 'key', '', 'form-select select2', true); ?>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <?php echo Form::FormDate('hakedis_tarihi', date('d.m.Y'), 'Hakediş Tarihi *', 'bx bx-calendar', 'form-control flatpickr'); ?>
                        </div>
                        <div class="col-md-6">
                            <?php echo Form::FormFloatInput('text', 'gun', '', 'Örn: 14', 'Hakediş (Gün) *', 'bx bx-time', 'form-control', true); ?>
                        </div>
                    </div>
                    <div class="mb-0">
                        <?php echo Form::FormFloatTextarea('aciklama', '', 'Açıklama', 'Açıklama', 'bx bx-comment', 'form-control', false, '80px', 3); ?>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">İptal</button>
                <button type="button" class="btn btn-primary" id="btnManuelKaydet"><i class="bx bx-save me-1"></i>Kaydet</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="excelModal" tabindex="-1" aria-labelledby="excelModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="excelModalLabel"><i class="bx bx-spreadsheet me-2"></i>Excel ile Manuel Hakediş Yükle</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body">
                <p class="font-size-12 text-muted mb-2">Şablonda aktif personel TC ve adlarıyla hazır gelir. Yalnızca <strong>Hakediş Tarihi</strong> ve <strong>Gün</strong> doldurduğunuz satırlar eklenir; Gün boş satırlar atlanır. Aynı personel, tarih ve gün için mevcut kayıt varsa tekrar eklenmez.</p>
                <a class="btn btn-sm btn-outline-primary mb-3" href="views/personel/api/APIyillik-izin-hakedisleri.php?action=manuel-hakedis-sablon"><i class="bx bx-download me-1"></i>Şablonu İndir</a>
                <form id="excelForm">
                    <input type="file" class="form-control" id="excel_file" name="excel_file" accept=".xlsx,.xls" required>
                </form>
                <div id="excelSonuc" class="mt-3 font-size-12"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Kapat</button>
                <button type="button" class="btn btn-primary" id="btnExcelYukle"><i class="bx bx-upload me-1"></i>Yükle</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
<script src="views/personel/js/yillik-izin-hakedisleri.js?v=<?php echo time(); ?>"></script>
