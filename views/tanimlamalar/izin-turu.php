<?php

require_once dirname(__DIR__, 2) . '/Autoloader.php';
use App\Helper\Form;
use App\Model\TanimlamalarModel;

$maintitle = "Tanımlamalar";
$title = "İzin Türü Tanımlamaları";

$Tanimlamalar = new TanimlamalarModel();
$summary = $Tanimlamalar->izinTuruSummary((int) ($_SESSION['firma_id'] ?? 0));
?>
<script>try { document.documentElement.classList.toggle('izinturu-summary-hidden', localStorage.getItem('izinturu_summary_cards_state') === 'hidden'); } catch (e) {}</script>
<style>
#summaryCardsContainer { overflow: hidden; max-height: 1100px; opacity: 1; transition: max-height .3s ease, opacity .3s ease, margin .3s ease; }
.izinturu-summary-hidden #summaryCardsContainer { max-height: 0 !important; opacity: 0; margin-top: 0 !important; margin-bottom: 0 !important; pointer-events: none; }
@media (prefers-reduced-motion: reduce) { #summaryCardsContainer { transition: none; } }
#izinTuruTable tbody tr { cursor: pointer; }
</style>

<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />

<?php include 'layouts/breadcrumb.php'; ?>

<div class="container-fluid">
    <div class="row align-items-center mb-3">
        <div class="col-md-6 col-12 d-flex align-items-center gap-3">
            <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 44px; height: 44px;">
                <i class="bx bx-calendar-check fs-4 text-primary"></i>
            </div>
            <div>
                <h4 class="mb-0 fw-bold text-dark font-size-16">İzin Türleri</h4>
                <p class="text-muted mb-0 font-size-12">Personel izin taleplerinde kullanılan izin türleri, ücret ve puantaj kuralları</p>
            </div>
        </div>

        <div class="personel-action-toolbar col-md-6 col-12 d-flex align-items-center justify-content-md-end gap-2 mt-2 mt-md-0">
            <button type="button" class="btn btn-primary top-action-btn shadow-sm text-white" id="actionEkle" data-bs-toggle="modal" data-bs-target="#actionModal">
                <i class="bx bx-plus font-size-16"></i> Yeni İzin Türü
            </button>

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
                        <span class="summary-kpi-label">TOPLAM İZİN TÜRÜ</span>
                        <div class="summary-kpi-icon bg-primary-subtle text-primary border border-primary-subtle"><i class="bx bx-layer"></i></div>
                    </div>
                    <h3 class="summary-kpi-value my-1" id="kpi_toplam"><?php echo (int) $summary->toplam; ?></h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext" id="kpi_sub_toplam">Ücretli: <?php echo (int) $summary->ucretli; ?> | Ücretsiz: <?php echo (int) $summary->ucretsiz; ?></span>
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
                        <span class="summary-kpi-label">ÜCRETLİ İZİNLER</span>
                        <div class="summary-kpi-icon bg-success-subtle text-success border border-success-subtle"><i class="bx bx-money"></i></div>
                    </div>
                    <h3 class="summary-kpi-value my-1 text-success" id="kpi_ucretli"><?php echo (int) $summary->ucretli; ?></h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext text-success fw-semibold">Maaştan kesilmeyen</span>
                        <button type="button" class="btn btn-sm btn-subtle-success rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn" data-status="ucretli">
                            <i class="bx bx-check-circle"></i> Ücretli
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">ÜCRETSİZ İZİNLER</span>
                        <div class="summary-kpi-icon bg-danger-subtle text-danger border border-danger-subtle"><i class="bx bx-block"></i></div>
                    </div>
                    <h3 class="summary-kpi-value my-1 text-danger" id="kpi_ucretsiz"><?php echo (int) $summary->ucretsiz; ?></h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext text-danger fw-semibold">Maaştan kesilen</span>
                        <button type="button" class="btn btn-sm btn-subtle-danger rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn" data-status="ucretsiz">
                            <i class="bx bx-minus-circle"></i> Ücretsiz
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">YETKİLİ ONAYLI</span>
                        <div class="summary-kpi-icon bg-warning-subtle text-warning border border-warning-subtle"><i class="bx bx-lock-alt"></i></div>
                    </div>
                    <h3 class="summary-kpi-value my-1" id="kpi_onayli"><?php echo (int) $summary->onayli; ?></h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext" id="kpi_sub_personel">Personele açık: <?php echo (int) $summary->personel_gorebilir; ?></span>
                        <button type="button" class="btn btn-sm btn-outline-warning rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn" data-status="onayli">
                            <i class="bx bx-lock-alt"></i> Onaylı
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0" id="izinTuruListCard">
        <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
                <div class="bg-primary-subtle text-primary rounded-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                    <i class="bx bx-list-ul font-size-18"></i>
                </div>
                <div>
                    <h5 class="card-title font-size-14 fw-bold mb-0 text-dark">İzin Türleri Listesi</h5>
                    <p class="text-muted font-size-11 mb-0">Anlık filtreleme ve sıralama; satıra tıklayarak düzenleyebilirsiniz</p>
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
                <table id="izinTuruTable" class="table table-bordered table-hover nowrap align-middle w-100 mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="text-center" style="width: 50px;" data-filter="none">Sıra</th>
                            <th data-filter="string">İzin Türü</th>
                            <th class="text-center" style="width: 90px;" data-filter="string">Kısa Kod</th>
                            <th class="text-center" data-filter="none">Görünüm (PWA)</th>
                            <th class="text-center" style="width: 110px;" data-filter="select" data-filter-options="Ücretli|Ücretsiz">Ücret Durumu</th>
                            <th class="text-center" style="width: 130px;" data-filter="select" data-filter-options="Evet|Hayır">Puantajda "X" Say</th>
                            <th class="text-center" style="width: 120px;" data-filter="select" data-filter-options="Evet|Hayır">Personel Görebilir</th>
                            <th class="text-center" style="width: 110px;" data-filter="select" data-filter-options="Evet|Hayır">Yetkili Onayı</th>
                            <th data-filter="string">Açıklama</th>
                            <th class="text-center" style="width: 100px;" data-filter="none">İşlem</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="actionModal" tabindex="-1" aria-labelledby="actionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="actionModalLabel"><i class="bx bx-calendar-check me-2"></i>İzin Türü
                    İşlemleri</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="actionForm">
                    <input type="hidden" name="izin_turu_id" id="izin_turu_id" class="form-control" value="0">

                    <div class="row mb-3">

                        <div class="col-md-8">
                            <?php echo
                                Form::FormFloatInput(
                                    "text",
                                    "izin_turu",
                                    "",
                                    "İzin Türü giriniz!",
                                    "İzin Türü",
                                    "calendar",
                                    "form-control"

                                ); ?>
                        </div>
                        <div class="col-md-4">
                            <?php echo
                                Form::FormFloatInput(
                                    "text",
                                    "kisa_kod",
                                    "",
                                    "Kısa kod giriniz!",
                                    "Kısa Kod",
                                    "code",
                                    "form-control"

                                ); ?>
                        </div>
                    </div>


                    <div class="row mb-3">
                        <div class="col-md-6">
                            <?php echo
                                Form::FormSelect2(
                                    name: "renk",
                                    options: [
                                        "bg-primary/10 text-primary" => "Varsayılan (Mavi)",
                                        "bg-blue-100 dark:bg-blue-900/30 text-blue-600" => "Mavi (Yıllık)",
                                        "bg-amber-100 dark:bg-amber-900/30 text-amber-600" => "Turuncu (Mazeret)",
                                        "bg-red-100 dark:bg-red-900/30 text-red-600" => "Kırmızı (Hastalık)",
                                        "bg-pink-100 dark:bg-pink-900/30 text-pink-600" => "Pembe (Doğum)",
                                        "bg-gray-100 dark:bg-gray-900/30 text-gray-600" => "Gri (Ücretsiz)",
                                        "bg-green-100 dark:bg-green-900/30 text-green-600" => "Yeşil",
                                        "bg-purple-100 dark:bg-purple-900/30 text-purple-600" => "Mor",
                                    ],
                                    selectedValue: '',
                                    label: 'Renk',
                                    icon: 'columns',
                                    class: 'form-control select2'



                                ); ?>
                        </div>

                        <div class="col-md-6">
                            <?php echo
                                Form::FormSelect2(
                                    name: "ikon",
                                    options: [
                                        "event" => "Takvim (Varsayılan)",
                                        "beach_access" => "Plaj (Yıllık)",
                                        "event_note" => "Notlu Takvim (Mazeret)",
                                        "medical_services" => "Sağlık (Hastalık)",
                                        "child_friendly" => "Bebek (Doğum)",
                                        "money_off" => "Para Yok (Ücretsiz)",
                                        "flight" => "Uçak",
                                        "home" => "Ev",
                                        "work_off" => "İş Yok",
                                        "celebration" => "Kutlama",

                                    ],
                                    selectedValue: '',
                                    label: 'İkon',
                                    icon: 'home',
                                    class: 'form-control select2'



                                ); ?>
                        </div>



                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <div class="card border mb-0 h-100 shadow-none">
                                <div class="card-body p-3">
                                    <div class="form-check form-switch form-switch-md mb-2">
                                        <input class="form-check-input" type="checkbox" id="ucretli_mi"
                                            name="ucretli_mi" checked>
                                        <label class="form-check-label fw-medium d-flex align-items-center" for="ucretli_mi">
                                            <i class="bx bx-money text-success fs-5 me-2"></i>Ücretli İzin
                                        </label>
                                    </div>
                                    <small class="text-muted d-block ps-5">Maaştan kesilmez</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card border mb-0 h-100 shadow-none">
                                <div class="card-body p-3">
                                    <div class="form-check form-switch form-switch-md mb-2">
                                        <input class="form-check-input" type="checkbox" id="normal_mesai_sayilir"
                                            name="normal_mesai_sayilir">
                                        <label class="form-check-label fw-medium d-flex align-items-center" for="normal_mesai_sayilir">
                                            <i class="bx bx-briefcase text-primary fs-5 me-2"></i>Çalışıyor Görünsün
                                        </label>
                                    </div>
                                    <small class="text-muted d-block ps-5">İzinli listesinde çıkmaz</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card border border-warning mb-0 h-100 shadow-none">
                                <div class="card-body p-3">
                                    <div class="form-check form-switch form-switch-md mb-2">
                                        <input class="form-check-input" type="checkbox" id="resmi_tatil_calismasi"
                                            name="resmi_tatil_calismasi">
                                        <label class="form-check-label fw-medium d-flex align-items-center" for="resmi_tatil_calismasi">
                                            <i class="bx bx-star text-warning fs-5 me-2"></i>Resmi Tatil Çalışması
                                        </label>
                                    </div>
                                    <small class="text-muted d-block ps-5">Bordoda resmi alacağı artırır (asgari/30 × gün)</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card border border-purple mb-0 h-100 shadow-none" style="border-color: #9333ea !important;">
                                <div class="card-body p-3">
                                    <div class="form-check form-switch form-switch-md mb-2">
                                        <input class="form-check-input" type="checkbox" id="hafta_tatili_calismasi"
                                            name="hafta_tatili_calismasi">
                                        <label class="form-check-label fw-medium d-flex align-items-center" for="hafta_tatili_calismasi">
                                            <i class="bx bx-work-alt fs-5 me-2" style="color:#9333ea"></i>Hafta Tatili Çalışması
                                        </label>
                                    </div>
                                    <small class="text-muted d-block ps-5">Resmi alacağı artırır + maaşa ek ödeme (maaş/30 × gün)</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card border mb-0 h-100 shadow-none">
                                <div class="card-body p-3">
                                    <div class="form-check form-switch form-switch-md mb-2">
                                        <input class="form-check-input" type="checkbox" id="personel_gorebilir"
                                            name="personel_gorebilir" checked>
                                        <label class="form-check-label fw-medium d-flex align-items-center" for="personel_gorebilir">
                                            <i class="bx bx-show text-info fs-5 me-2"></i>Personel Görebilir
                                        </label>
                                    </div>
                                    <small class="text-muted d-block ps-5">Talepte görünür</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card border mb-0 h-100 shadow-none">
                                <div class="card-body p-3">
                                    <div class="form-check form-switch form-switch-md mb-2">
                                        <input class="form-check-input" type="checkbox" id="yetkili_onayina_tabi"
                                            name="yetkili_onayina_tabi">
                                        <label class="form-check-label fw-medium d-flex align-items-center" for="yetkili_onayina_tabi">
                                            <i class="bx bx-lock-alt text-warning fs-5 me-2"></i>Yetkili Onayı
                                        </label>
                                    </div>
                                    <small class="text-muted d-block ps-5">Onay sonrası kilitlenir</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-12">
                            <?php echo
                                Form::FormFloatTextarea(
                                    "aciklama",
                                    "",
                                    "Açıklama giriniz",
                                    "Açıklama",
                                    "file-text",


                                ); ?>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary waves-effect btn-label waves-light float-start"
                    data-bs-dismiss="modal"><i class="bx bx-x label-icon"></i>Kapat</button>
                <button type="button" id="actionKaydet"
                    class="btn btn-primary waves-effect btn-label waves-light float-end"><i
                        class="bx bx-save label-icon"></i>Kaydet</button>
            </div>
        </div>
    </div>
</div>

<script src="views/tanimlamalar/js/izin-turu.js?v=<?php echo time(); ?>"></script>